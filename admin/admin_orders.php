<?php
session_start(); 
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../user/login.php"); exit; }

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $con->real_escape_string($_POST['new_status']);
    $stmt = $con->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    if ($stmt->execute()) { $msg = "Order status updated successfully!"; }
}

$orders = $con->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Meryll Eatery Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- ✅ IDENTICAL SIDEBAR START -->
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" onclick="closeSidebar()"><i class="fas fa-arrow-left"></i></button>
        <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="admin_orders.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
            <li class="nav-item"><a href="get_order_details.php" class="nav-link"><i class="fas fa-file-invoice"></i> Order Details</a></li>
            <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
            <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
            <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>
    <!-- ✅ IDENTICAL SIDEBAR END -->

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <main class="main-content">
        <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
            <?php if ($msg): ?><div class="alert-msg success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div><?php endif; ?>
            
            <div class="admin-header">
                <div><h1>Order Management</h1><p style="color: var(--admin-text-muted, #64748b);">View, update, and track all customer orders.</p></div>
            </div>

            <div class="admin-card" style="padding: 0; overflow: hidden;">
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead><tr><th>Order #</th><th>Customer</th><th>Type</th><th>Time</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($orders && $orders->num_rows > 0): ?>
                            <?php while($row = $orders->fetch_assoc()): 
                                $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                            ?>
                                <tr>
                                    <td style="font-weight: 700;">#<?php echo htmlspecialchars($row['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['order_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['pickup_time']); ?></td>
                                    <td style="font-weight: 700;">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                    <td>
                                        <a href="get_order_details.php?id=<?php echo $row['order_id']; ?>" class="btn-view" style="text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 3rem; color: var(--admin-text-muted, #64748b);">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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