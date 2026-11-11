<?php
session_start(); include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../user/login.php"); exit; }

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $stmt = $con->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $_POST['new_status'], $_POST['order_id']);
    if ($stmt->execute()) $msg = "Status updated!";
}

// ✅ Now fetches username AND student_id (ID number)
$orders = $con->query("SELECT o.*, u.username, u.student_id FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders</title>
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
        <a href="admin_orders.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
        <a href="get_order_details.php" class="nav-link"><i class="fas fa-receipt"></i> Order Details</a>
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
        <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <h1 class="mb-1">Order Management</h1>
        <p class="text-muted mb-4">View, update, and track all customer orders.</p>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>ID Number</th>
                                <th>Type</th>
                                <th>Time</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($r = $orders->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">#<?= htmlspecialchars($r['order_number']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['username']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($r['email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($r['student_id'] ?: '—') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($r['order_type']) ?></td>
                                <td><?= htmlspecialchars($r['pickup_time']) ?></td>
                                <td class="fw-bold">₱<?= number_format($r['total_amount'], 2) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td>
                                    <a href="get_order_details.php?id=<?= $r['order_id'] ?>" class="btn btn-sm btn-primary me-1">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($r['payment_method'] === 'GCash' && !empty($r['gcash_proof'])): ?>
                                        <a href="../user/gcash_proof.php?order_id=<?= $r['order_id'] ?>" target="_blank" class="btn btn-sm btn-info" title="View GCash Receipt">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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