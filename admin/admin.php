<?php
session_start();
include '../connect.php';

// ✅ ROLE-BASED SECURITY: Kick out anyone who isn't an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

$admin_name = $_SESSION['username'];
$admin_id = $_SESSION['user_id']; // Assuming user_id is used for admin too

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
<!-- Link to your existing admin_style.css if you have one -->
<link rel="stylesheet" href="admin_style.css"> 
<style>
/* Dashboard & Notif Styles */
:root { --brand-red: #8B0000; --bg-light: #f8f9fa; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); margin: 0; display: flex; min-height: 100vh; }

/* Sidebar */
.sidebar { width: 260px; background: #fff; border-right: 1px solid #e9ecef; display: flex; flex-direction: column; position: fixed; height: 100vh; }
.sidebar-header { padding: 1.5rem; font-size: 1.2rem; font-weight: 700; color: var(--brand-red); border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 10px; }
.sidebar-nav { padding: 1rem 0; flex: 1; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 1.5rem; color: #333; text-decoration: none; font-weight: 500; transition: all 0.2s; }
.nav-item:hover, .nav-item.active { background: #fff0f0; color: var(--brand-red); border-left: 3px solid var(--brand-red); }
.nav-item i { font-size: 1.1rem; width: 20px; text-align: center; }

/* Main Content */
.main-content { flex: 1; margin-left: 260px; display: flex; flex-direction: column; }
.top-header { background: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e9ecef; position: sticky; top: 0; z-index: 50; }
.header-right { display: flex; align-items: center; gap: 20px; }

/* Notification Bell */
.notif-bell { position: relative; cursor: pointer; padding: 8px; border-radius: 50%; transition: background 0.2s; }
.notif-bell:hover { background: #f0f0f0; }
.notif-bell i { font-size: 1.4rem; color: #333; }
.notif-badge { position: absolute; top: 2px; right: 2px; background: #ff0000; color: #fff; font-size: 0.65rem; font-weight: 700; min-width: 16px; height: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: center; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }

/* Notif Dropdown */
.notif-dropdown { position: absolute; top: 50px; right: 0; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); width: 320px; max-height: 400px; overflow-y: auto; display: none; z-index: 1000; border: 1px solid #e9ecef; }
.notif-dropdown.show { display: block; }
.notif-header { padding: 15px; border-bottom: 2px solid #f0f0f0; font-weight: 700; color: var(--brand-red); display: flex; justify-content: space-between; align-items: center; }
.notif-item { padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; }
.notif-item:hover { background: #fff0f0; }
.notif-item.unread { background: #fff5f5; border-left: 3px solid var(--brand-red); }
.notif-item-title { font-weight: 600; color: #333; margin-bottom: 4px; font-size: 0.9rem; }
.notif-item-msg { font-size: 0.8rem; color: #6c757d; }
.notif-item-time { font-size: 0.7rem; color: #999; margin-top: 6px; }

/* Popup Notification */
.notif-popup { position: fixed; top: 20px; right: 20px; background: #fff; border-left: 4px solid var(--brand-red); border-radius: 8px; padding: 16px 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); z-index: 9999; display: none; animation: slideIn 0.5s ease; max-width: 350px; }
.notif-popup.show { display: block; }
@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.notif-popup-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.notif-popup-icon { font-size: 1.3rem; color: var(--brand-red); }
.notif-popup-title { font-weight: 700; color: #333; font-size: 0.95rem; }
.notif-popup-msg { color: #6c757d; font-size: 0.85rem; }

.dashboard-content { padding: 2rem; }
.welcome-card { background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 2rem; }
</style>
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
        <i class="bi bi-building"></i> Meryll Eatery
    </div>
    <nav class="sidebar-nav">
        <a href="admin.php" class="nav-item active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="inventory.php" class="nav-item"><i class="bi bi-box-seam"></i> Inventory</a>
        <a href="orders.php" class="nav-item"><i class="bi bi-receipt"></i> Orders</a>
        <a href="reports.php" class="nav-item"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="../user/logout.php" class="nav-item"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content">
    <header class="top-header">
        <h2>Dashboard</h2>
        <div class="header-right">
            <!-- Notification Bell -->
            <div class="notif-bell" onclick="toggleNotifDropdown(event)">
                <i class="bi bi-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?= $unread_count ?></span>
                <?php endif; ?>
                
                <!-- Dropdown -->
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <a href="mark_all_read.php" style="font-size:0.75rem;color:var(--brand-red);text-decoration:none;">Mark all read</a>
                    </div>
                    <?php if ($recent_notifs->num_rows > 0): ?>
                        <?php while($notif = $recent_notifs->fetch_assoc()): ?>
                            <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>" onclick="markAsRead(<?= $notif['notification_id'] ?>)">
                                <div class="notif-item-title"><?= htmlspecialchars($notif['title']) ?></div>
                                <div class="notif-item-msg"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notif-item-time"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: #999; font-size: 0.85rem;">No notifications yet</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display:flex;align-items:center;gap:8px;font-weight:600;">
                <i class="bi bi-person-circle" style="font-size:1.5rem;color:var(--brand-red)"></i>
                <span><?= htmlspecialchars($admin_name) ?></span>
            </div>
        </div>
    </header>

    <div class="dashboard-content">
        <div class="welcome-card">
            <h2>Welcome back, <?= htmlspecialchars($admin_name) ?>! 👋</h2>
            <p style="color:#6c757d;margin-top:0.5rem">Here is an overview of your store today.</p>
        </div>
        <!-- Add your stats cards or order tables here -->
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
    const bell = document.querySelector('.notif-bell');
    if (!bell.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Mark as read (you can create an AJAX endpoint for this later)
function markAsRead(id) {
    window.location.href = 'view_order.php?id=' + id; // Or your specific order view page
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
    
    // Play a subtle sound (optional)
    // new Audio('notif.mp3').play().catch(e => {});

    // Hide after 6 seconds
    setTimeout(() => {
        popup.classList.remove('show');
    }, 6000);
}

// Start polling
setInterval(checkNewOrders, 30000);
// Check immediately on load
checkNewOrders();
</script>

</body>
</html>