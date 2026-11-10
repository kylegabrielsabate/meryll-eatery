<?php
session_start();
include '../connect.php';

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
$unread_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Fetch recent notifications
$stmt = $con->prepare("SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$recent_notifs = $stmt->get_result();

// ✅ FETCH REAL STATS FROM DATABASE
// 1. Pending Orders
$stmt = $con->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// 2. Completed Today
$stmt = $con->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'Completed' AND DATE(created_at) = CURDATE()");
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// 3. Total Revenue (All completed orders)
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="admin_style.css">
<style>
/* Large Centered Notification Modal */
.order-alert-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(5px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}
.order-alert-modal.show {
    display: flex;
}
.order-alert-content {
    background: #fff;
    border-radius: 20px;
    padding: 3rem;
    max-width: 600px;
    width: 90%;
    text-align: center;
    box-shadow: 0 30px 80px rgba(0,0,0,0.4);
    animation: slideUp 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
    border: 4px solid #8B0000;
}
.order-alert-icon {
    width: 100px;
    height: 100px;
    background: #fef2f2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 3rem;
    color: #8B0000;
    animation: bounce 1s infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.order-alert-title {
    font-size: 2rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 1rem;
}
.order-alert-message {
    font-size: 1.2rem;
    color: #64748b;
    margin-bottom: 2rem;
    line-height: 1.6;
}
.order-alert-details {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: left;
}
.order-alert-details p {
    margin: 0.5rem 0;
    display: flex;
    justify-content: space-between;
    font-size: 1rem;
}
.order-alert-details strong {
    color: #0f172a;
}
.order-alert-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}
.btn-view-order {
    background: #8B0000;
    color: #fff;
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-view-order:hover {
    background: #6d0000;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(139, 0, 0, 0.3);
}
.btn-dismiss {
    background: #f1f5f9;
    color: #0f172a;
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-dismiss:hover {
    background: #e2e8f0;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
</head>
<body>

<!-- ✅ LARGE CENTERED NOTIFICATION MODAL -->
<div class="order-alert-modal" id="orderAlertModal">
    <div class="order-alert-content">
        <div class="order-alert-icon">
            <i class="bi bi-bell-fill"></i>
        </div>
        <h2 class="order-alert-title">🔔 New Order Received!</h2>
        <p class="order-alert-message" id="alertMessage">A customer has placed a new order online.</p>
        
        <div class="order-alert-details">
            <p><strong>Order #:</strong> <span id="alertOrderNum">-</span></p>
            <p><strong>Customer:</strong> <span id="alertCustomer">-</span></p>
            <p><strong>Total Amount:</strong> <span id="alertAmount" style="color:#8B0000;font-size:1.2rem;font-weight:700">₱0.00</span></p>
            <p><strong>Time:</strong> <span id="alertTime">-</span></p>
        </div>
        
        <div class="order-alert-actions">
            <button class="btn-view-order" onclick="viewOrderFromAlert()">
                <i class="bi bi-eye"></i> View Order
            </button>
            <button class="btn-dismiss" onclick="dismissAlert()">
                <i class="bi bi-x-circle"></i> Dismiss
            </button>
        </div>
    </div>
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

        <!-- ✅ Stats Grid with REAL DATABASE DATA -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-receipt"></i>
                </div>
                <div class="stat-info">
                    <h3>Pending Orders</h3>
                    <p class="stat-number"><?= $pending_count ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Completed Today</h3>
                    <p class="stat-number"><?= $completed_count ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">₱<?= number_format($total_revenue, 2) ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
let currentOrderId = null;
let lastCheckedOrder = null;

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
    window.location.href = 'get_order_details.php?id=' + id;
}

// ✅ PLAY NOTIFICATION SOUND
function playNotificationSound() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 523.25; // C5 note
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
    
    setTimeout(() => {
        const osc2 = audioContext.createOscillator();
        const gain2 = audioContext.createGain();
        osc2.connect(gain2);
        gain2.connect(audioContext.destination);
        osc2.frequency.value = 783.99; // G5 note
        osc2.type = 'sine';
        gain2.gain.setValueAtTime(0.3, audioContext.currentTime);
        gain2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        osc2.start(audioContext.currentTime);
        osc2.stop(audioContext.currentTime + 0.5);
    }, 200);
}

// ✅ VIBRATE DEVICE (for mobile)
function vibrateDevice() {
    if ('vibrate' in navigator) {
        navigator.vibrate([200, 100, 200]);
    }
}

// ✅ SHOW LARGE CENTERED ALERT
function showLargeAlert(orderData) {
    currentOrderId = orderData.order_id;
    
    document.getElementById('alertOrderNum').textContent = orderData.order_number;
    document.getElementById('alertCustomer').textContent = orderData.customer_name || 'Online Customer';
    document.getElementById('alertAmount').textContent = '₱' + parseFloat(orderData.total_amount).toFixed(2);
    document.getElementById('alertTime').textContent = new Date().toLocaleString();
    
    const modal = document.getElementById('orderAlertModal');
    modal.classList.add('show');
    
    playNotificationSound();
    vibrateDevice();
    
    setTimeout(() => {
        if (modal.classList.contains('show')) {
            dismissAlert();
        }
    }, 15000);
}

// View order from alert
function viewOrderFromAlert() {
    if (currentOrderId) {
        window.location.href = 'get_order_details.php?id=' + currentOrderId;
    }
}

// Dismiss alert
function dismissAlert() {
    document.getElementById('orderAlertModal').classList.remove('show');
    currentOrderId = null;
}

// ✅ REAL-TIME ORDER CHECKING (Every 5 seconds)
function checkNewOrders() {
    fetch('check_new_orders.php')
        .then(r => r.json())
        .then(data => {
            if (data.has_new_order) {
                if (data.order_id !== lastCheckedOrder) {
                    lastCheckedOrder = data.order_id;
                    showLargeAlert(data);
                    
                    fetch('update_notification_count.php')
                        .then(r => r.json())
                        .then(countData => {
                            const badge = document.querySelector('.notif-badge');
                            if (countData.count > 0) {
                                if (badge) {
                                    badge.textContent = countData.count;
                                    badge.style.display = 'flex';
                                }
                            }
                        });
                }
            }
        })
        .catch(err => console.log('Polling error:', err));
}

setInterval(checkNewOrders, 5000);
checkNewOrders();
</script>

</body>
</html>