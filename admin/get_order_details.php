<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    
    $stmt = $con->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    
    header("Location: get_order_details.php?id=" . $order_id . "&msg=updated");
    exit();
}

$view_id = $_GET['id'] ?? $_GET['order_id'] ?? $_GET['notif_id'] ?? null;

$order = null;
$order_items = [];
if ($view_id) {
    $stmt = $con->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if ($order) {
        $stmt2 = $con->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt2->bind_param("i", $view_id);
        $stmt2->execute();
        $order_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$stmt_list = $con->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT 20");
$stmt_list->execute();
$recent_orders = $stmt_list->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Details - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
</head>
<body>

<button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <button class="sidebar-close" onclick="toggleSidebar()"><i class="fas fa-arrow-left"></i></button>
    <a href="admin.php" class="logo">
        <span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery
    </a>
    <ul class="nav-menu">
        <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="nav-item"><a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
        <li class="nav-item"><a href="get_order_details.php" class="nav-link active"><i class="fas fa-receipt"></i> Order Details</a></li>
        <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
        <li class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a></li>
        <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
        <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
        <div class="admin-header">
            <div>
                <h1><?= $order ? 'Order #' . htmlspecialchars($order['order_number']) : 'All Orders' ?></h1>
                <p style="color: var(--admin-text-muted, #64748b);"><?= $order ? 'View and manage this order' : 'Recent customer orders' ?></p>
            </div>
            <?php if ($order): ?>
                <a href="get_order_details.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert-msg success"><i class="fas fa-check-circle"></i> Order status updated successfully!</div>
        <?php endif; ?>

        <?php if ($order): ?>
        <div class="order-info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="admin-card">
                <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;"><i class="fas fa-info-circle" style="color:#8B0000;"></i> Order Information</h3>
                <div class="info-box" style="background:#f8fafc; padding:1rem; border-radius:10px; margin-bottom:0.8rem;">
                    <div class="info-label" style="font-size:0.7rem; color:#64748b; font-weight:700; text-transform:uppercase;">Order Number</div>
                    <div class="info-value" style="font-size:1.1rem; font-weight:700; color:#0f172a;"><?= htmlspecialchars($order['order_number']) ?></div>
                </div>
                <div class="info-box" style="background:#f8fafc; padding:1rem; border-radius:10px; margin-bottom:0.8rem;">
                    <div class="info-label">Date & Time</div>
                    <div class="info-value"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></div>
                </div>
                <div class="info-box" style="background:#f8fafc; padding:1rem; border-radius:10px; margin-bottom:0.8rem;">
                    <div class="info-label">Order Type</div>
                    <div class="info-value"><?= htmlspecialchars($order['order_type']) ?></div>
                </div>
                <div class="info-box" style="background:#f8fafc; padding:1rem; border-radius:10px; margin-bottom:0.8rem;">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value"><?= htmlspecialchars($order['payment_method']) ?></div>
                </div>
                <div class="info-box" style="background:#f8fafc; padding:1rem; border-radius:10px; margin-bottom:0.8rem;">
                    <div class="info-label">Total Amount</div>
                    <div class="info-value" style="font-size:1.5rem; color:#8B0000;">₱<?= number_format($order['total_amount'], 2) ?></div>
                </div>
                <?php if (!empty($order['order_note'])): ?>
                    <div class="info-box" style="background:#f8fafc; padding:1rem; border-radius:10px;">
                        <div class="info-label">Customer Notes</div>
                        <div class="info-value" style="font-size:0.95rem;"><?= htmlspecialchars($order['order_note']) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="admin-card">
                <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;"><i class="fas fa-check-square" style="color:#8B0000;"></i> Order Status Checker</h3>
                <p style="color:#64748b; margin-bottom:1rem; font-size:0.9rem;">Update the status of this order as you prepare it.</p>
                
                <div style="margin-bottom:1.5rem;">
                    <div class="info-label">Current Status</div>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>" style="font-size:1rem; padding:8px 16px; display:inline-block; margin-top:0.5rem;">
                        <?= htmlspecialchars($order['status']) ?>
                    </span>
                </div>

                <form method="POST" action="get_order_details.php?id=<?= $order['order_id'] ?>">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <div class="form-group">
                        <label style="display:block; font-size:0.8rem; color:#64748b; font-weight:700; margin-bottom:0.4rem; text-transform:uppercase; letter-spacing:0.5px;">Update Status To:</label>
                        <select name="status" style="width:100%; padding:0.75rem 1rem; border:2px solid #e2e8f0; border-radius:10px; font-size:1rem; font-family:inherit; margin-bottom:1rem; background:#fff;" required>
                            <option value="Pending" <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>⏳ Pending</option>
                            <option value="Preparing" <?= $order['status'] == 'Preparing' ? 'selected' : '' ?>>👨‍🍳 Preparing</option>
                            <option value="Ready" <?= $order['status'] == 'Ready' ? 'selected' : '' ?>>✅ Ready for Pickup</option>
                            <option value="Completed" <?= $order['status'] == 'Completed' ? 'selected' : '' ?>>🎉 Completed</option>
                            <option value="Cancelled" <?= $order['status'] == 'Cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn-primary" style="width:100%; padding:0.85rem; border:none; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;"><i class="fas fa-list-ul" style="color:#8B0000;"></i> Order Items</h3>
            <!-- ✅ USE table-scroll-wrapper HERE -->
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱<?= number_format($item['price'], 2) ?></td>
                            <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:700;">Total:</td>
                            <td style="font-weight:800; color:#8B0000; font-size:1.1rem;">₱<?= number_format($order['total_amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="admin-card">
            <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;"><i class="fas fa-clock" style="color:#8B0000;"></i> Recent Orders</h3>
            <?php if ($recent_orders->num_rows > 0): ?>
                <!-- ✅ USE table-scroll-wrapper HERE -->
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $recent_orders->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['order_number']) ?></strong></td>
                                <td><?= date('M j, g:i A', strtotime($row['created_at'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="get_order_details.php?id=<?= $row['order_id'] ?>" class="btn-view" style="text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align:center; padding:2rem; color:#64748b;">No orders found yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
    document.body.style.overflow = document.getElementById('sidebar').classList.contains('active') ? 'hidden' : '';
}
</script>

</body>
</html>