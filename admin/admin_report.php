<?php
session_start(); 
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../user/login.php"); exit; }

$monthly_data = [];
$res = $con->query("
    SELECT DATE_FORMAT(created_at, '%M %Y') as month_year, 
           COUNT(*) as total_orders, 
           SUM(total_amount) as revenue 
    FROM orders GROUP BY month_year ORDER BY MAX(created_at) DESC
");
while ($row = $res->fetch_assoc()) { $monthly_data[] = $row; }

$top_items_report = $con->query("
    SELECT item_name, SUM(quantity) as total_sold, SUM(price * quantity) as revenue
    FROM order_items GROUP BY item_name ORDER BY total_sold DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monthly Report | Meryll Eatery Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
<style>
@media print {
    .sidebar, .mobile-toggle, .sidebar-backdrop, .btn-print, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
}
</style>
</head>
<body>
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar d-flex flex-column" id="sidebar">
    <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
    <nav class="nav flex-column flex-grow-1">
        <a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
        <a href="get_order_details.php" class="nav-link"><i class="fas fa-receipt"></i> Order Details</a>
        <a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a>
        <a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_report.php" class="nav-link active"><i class="fas fa-chart-line"></i> Monthly Report</a>
    </nav>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
        <a href="../user/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="container-fluid p-0">
        <div class="print-header d-none d-print-block text-center mb-4">
            <h1>Meryll Eatery</h1>
            <p>Official Monthly Sales Report - Generated: <?= date('F d, Y') ?></p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h1 class="mb-1">Monthly Sales Report</h1>
                <p class="text-muted mb-0">Track revenue, order volume, and top-selling items.</p>
            </div>
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report</button>
        </div>

        <!-- Top Selling Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-trophy text-accent me-2"></i>Top 5 Most Sold Items</h5>
                <?php if ($top_items_report && $top_items_report->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Rank</th><th>Item Name</th><th>Total Quantity Sold</th><th>Total Revenue</th></tr></thead>
                            <tbody>
                                <?php $rank = 1; while($item = $top_items_report->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold">#<?= $rank++ ?></td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="fw-bold text-accent"><?= $item['total_sold'] ?> pcs/servings</td>
                                    <td class="fw-bold">₱<?= number_format($item['revenue'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No sales data available yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Breakdown -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-calendar-alt text-accent me-2"></i>Sales Breakdown by Month</h5>
                <?php if (empty($monthly_data)): ?>
                    <p class="text-muted text-center py-3">No order data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Month / Year</th><th>Total Orders</th><th>Total Revenue</th></tr></thead>
                            <tbody>
                                <?php $grandTotal = 0; $grandOrders = 0; foreach ($monthly_data as $data): 
                                    $grandTotal += $data['revenue']; $grandOrders += $data['total_orders']; ?>
                                    <tr>
                                        <td class="fw-bold"><?= $data['month_year'] ?></td>
                                        <td><?= $data['total_orders'] ?> orders</td>
                                        <td class="fw-bold text-accent">₱<?= number_format($data['revenue'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td class="fw-bold">GRAND TOTAL</td>
                                    <td class="fw-bold"><?= $grandOrders ?> orders</td>
                                    <td class="fw-bold text-accent fs-5">₱<?= number_format($grandTotal, 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
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