<?php
session_start();
include '../connect.php';

// ✅ ROLE-BASED SECURITY
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

// Handle Status Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    
    $stmt = $con->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    
    header("Location: get_order_details.php?id=" . $order_id . "&msg=updated");
    exit();
}

// ✅ SMART ID DETECTION
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

// Fetch recent orders for the list view
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="admin_style.css">
</head>
<body>

<!-- ✅ IDENTICAL SIDEBAR FOR ALL PAGES -->
<aside class="sidebar">
    <a href="admin.php" class="logo">
        <i class="bi bi-building-fill logo-icon"></i>
        <div>
            <div>Meryll Eatery</div>
            <small style="font-size:0.7rem;opacity:0.8">Admin Panel</small>
        </div>
    </a>
    <nav class="nav-menu">
        <a href="admin.php" class="nav-link"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="admin_inventory.php" class="nav-link"><i class="bi bi-box-seam"></i><span>Inventory</span></a>
        <a href="admin_orders.php" class="nav-link"><i class="bi bi-receipt"></i><span>Orders</span></a>
        <a href="admin_report.php" class="nav-link"><i class="bi bi-bar-chart-line"></i><span>Reports</span></a>
        <a href="get_order_details.php" class="nav-link active"><i class="bi bi-file-earmark-text"></i><span>Order Details</span></a>
    </nav>
    <div class="sidebar-footer">
        <a href="../user/logout.php" class="nav-link" style="color:#ef4444"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
</aside>

<main class="main-content">
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1><?= $order ? 'Order #' . htmlspecialchars($order['order_number']) : 'All Orders' ?></h1>
                <p><?= $order ? 'View and manage this order' : 'Recent customer orders' ?></p>
            </div>
            <?php if ($order): ?>
                <a href="get_order_details.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert-msg success"><i class="bi bi-check-circle-fill"></i> Order status updated successfully!</div>
        <?php endif; ?>

        <?php if ($order): ?>
        <!-- SINGLE ORDER DETAILS VIEW -->
        <div class="order-info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Order Info -->
            <div class="admin-card">
                <h3><i class="bi bi-info-circle"></i> Order Information</h3>
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

            <!-- Status Checker -->
            <div class="admin-card">
                <h3><i class="bi bi-check2-square"></i> Order Status Checker</h3>
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
                        <label style="display:block; font-size:0.8rem; color:#64748b; font-weight:700; margin-bottom:0.4rem;">Update Status To:</label>
                        <select name="status" style="width:100%; padding:0.75rem 1rem; border:2px solid #e2e8f0; border-radius:10px; font-size:1rem; font-family:inherit; margin-bottom:1rem;" required>
                            <option value="Pending" <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>⏳ Pending</option>
                            <option value="Preparing" <?= $order['status'] == 'Preparing' ? 'selected' : '' ?>>👨‍🍳 Preparing</option>
                            <option value="Ready" <?= $order['status'] == 'Ready' ? 'selected' : '' ?>>✅ Ready for Pickup</option>
                            <option value="Completed" <?= $order['status'] == 'Completed' ? 'selected' : '' ?>>🎉 Completed</option>
                            <option value="Cancelled" <?= $order['status'] == 'Cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn-update" style="width:100%; padding:0.85rem; background:#8B0000; color:#fff; border:none; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="bi bi-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Order Items Table -->
        <div class="admin-card">
            <h3><i class="bi bi-list-ul"></i> Order Items</h3>
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

        <?php else: ?>
        <!-- LIST OF ALL RECENT ORDERS -->
        <div class="admin-card">
            <h3><i class="bi bi-clock-history"></i> Recent Orders</h3>
            <?php if ($recent_orders->num_rows > 0): ?>
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
                                <!-- ✅ STANDARD REDIRECT LINK (No JS, just pure HTML) -->
                                <a href="get_order_details.php?id=<?= $row['order_id'] ?>" class="btn-view" style="background:#8B0000; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; padding:2rem; color:#64748b;">No orders found yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

</body>
</html>