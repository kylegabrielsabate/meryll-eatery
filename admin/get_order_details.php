<?php
session_start();
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../user/login.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $stmt = $con->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $_POST['status'], $_POST['order_id']);
    $stmt->execute();
    header("Location: get_order_details.php?id=" . $_POST['order_id'] . "&msg=updated");
    exit();
}

$view_id = $_GET['id'] ?? $_GET['order_id'] ?? $_GET['notif_id'] ?? null;
$order = null; $order_items = [];

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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Details - Meryll Eatery</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
</head>
<body>
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar d-flex flex-column" id="sidebar">
    <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
    <nav class="nav flex-column flex-grow-1">
        <a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
        <a href="get_order_details.php" class="nav-link active"><i class="fas fa-receipt"></i> Order Details</a>
        <a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a>
        <a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a>
    </nav>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
        <a href="../user/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
            <div>
                <h1 class="mb-1"><?= $order ? 'Order #' . htmlspecialchars($order['order_number']) : 'All Orders' ?></h1>
                <p class="text-muted mb-0"><?= $order ? 'View and manage this order' : 'Recent customer orders' ?></p>
            </div>
            <?php if ($order): ?>
                <a href="get_order_details.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>Order status updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
        <div class="row g-4 mb-4">
            <!-- Order Info Card -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="fas fa-info-circle text-accent me-2"></i>Order Information</h5>
                        <div class="bg-light rounded p-3 mb-2">
                            <small class="text-muted fw-bold text-uppercase d-block">Order Number</small>
                            <span class="fw-bold fs-5"><?= htmlspecialchars($order['order_number']) ?></span>
                        </div>
                        <div class="bg-light rounded p-3 mb-2">
                            <small class="text-muted fw-bold text-uppercase d-block">Date & Time</small>
                            <span><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="bg-light rounded p-3 mb-2">
                            <small class="text-muted fw-bold text-uppercase d-block">Order Type</small>
                            <span><?= htmlspecialchars($order['order_type']) ?></span>
                        </div>
                        <div class="bg-light rounded p-3 mb-2">
                            <small class="text-muted fw-bold text-uppercase d-block">Payment Method</small>
                            <span><?= htmlspecialchars($order['payment_method']) ?></span>
                        </div>
                        <div class="bg-light rounded p-3 mb-2">
                            <small class="text-muted fw-bold text-uppercase d-block">Total Amount</small>
                            <span class="fw-bold fs-4 text-accent">₱<?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                        <?php if (!empty($order['order_note'])): ?>
                            <div class="bg-light rounded p-3 mt-2">
                                <small class="text-muted fw-bold text-uppercase d-block">Customer Notes</small>
                                <span><?= htmlspecialchars($order['order_note']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Status Checker Card -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="fas fa-check-square text-accent me-2"></i>Order Status Checker</h5>
                        <p class="text-muted small">Update the status of this order as you prepare it.</p>
                        
                        <div class="mb-4">
                            <small class="text-muted fw-bold text-uppercase d-block mb-2">Current Status</small>
                            <span class="badge bg-secondary fs-6 px-3 py-2"><?= htmlspecialchars($order['status']) ?></span>
                        </div>

                        <form method="POST" action="get_order_details.php?id=<?= $order['order_id'] ?>">
                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Update Status To:</label>
                                <select name="status" class="form-select form-select-lg" required>
                                    <option value="Pending" <?= $order['status']=='Pending'?'selected':'' ?>>⏳ Pending</option>
                                    <option value="Preparing" <?= $order['status']=='Preparing'?'selected':'' ?>>👨‍🍳 Preparing</option>
                                    <option value="Ready" <?= $order['status']=='Ready'?'selected':'' ?>>✅ Ready for Pickup</option>
                                    <option value="Completed" <?= $order['status']=='Completed'?'selected':'' ?>>🎉 Completed</option>
                                    <option value="Cancelled" <?= $order['status']=='Cancelled'?'selected':'' ?>>❌ Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-save me-2"></i>Update Status
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-list-ul text-accent me-2"></i>Order Items</h5>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Item Name</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
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
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                <td class="fw-bold text-accent fs-5">₱<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Recent Orders List -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-clock text-accent me-2"></i>Recent Orders</h5>
                <?php if ($recent_orders->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Order #</th><th>Date</th><th>Type</th><th>Total</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php while($row = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['order_number']) ?></td>
                                    <td><?= date('M j, g:i A', strtotime($row['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($row['order_type']) ?></td>
                                    <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td>
                                        <a href="get_order_details.php?id=<?= $row['order_id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No orders found yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.querySelector('.sidebar-backdrop').classList.toggle('show');
}
</script>
</body>
</html>