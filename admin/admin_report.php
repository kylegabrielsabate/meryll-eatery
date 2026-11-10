<?php
session_start(); 
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../user/login.php"); exit; }

$monthly_data = [];
$res = $con->query("
    SELECT DATE_FORMAT(created_at, '%M %Y') as month_year, 
           COUNT(*) as total_orders, 
           SUM(total_amount) as revenue 
    FROM orders 
    GROUP BY month_year 
    ORDER BY MAX(created_at) DESC
");

while ($row = $res->fetch_assoc()) {
    $monthly_data[] = $row;
}

$top_items_report = $con->query("
    SELECT item_name, SUM(quantity) as total_sold, SUM(price * quantity) as revenue
    FROM order_items
    GROUP BY item_name
    ORDER BY total_sold DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report | Meryll Eatery Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" onclick="closeSidebar()"><i class="fas fa-arrow-left"></i></button>
        <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
            <li class="nav-item"><a href="get_order_details.php" class="nav-link"><i class="fas fa-file-invoice"></i> Order Details</a></li>
            <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
            <li class="nav-item"><a href="admin_report.php" class="nav-link active"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
            <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="admin-container" style="max-width: 1000px; margin: 0 auto; padding: 2rem;">
            <div class="print-header">
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Meryll Eatery</h1>
                <p style="font-size: 1rem; color: #555;">Official Monthly Sales Report</p>
                <p style="font-size: 0.9rem;">Generated on: <?php echo date('F d, Y'); ?></p>
            </div>
            <div class="admin-header no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
                <div><h1 style="font-size:1.8rem;font-weight:800;color:#0f172a;">Monthly Sales Report</h1><p style="color: #64748b;">Track revenue, order volume, and top-selling items.</p></div>
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
            </div>

            <div class="admin-card" style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);margin-bottom: 2rem;">
                <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;"><i class="fas fa-trophy" style="color:#8B0000;"></i> Top 5 Most Sold Items</h3>
                <?php if ($top_items_report && $top_items_report->num_rows > 0): ?>
                    <table class="data-table">
                        <thead><tr><th>Rank</th><th>Item Name</th><th>Total Quantity Sold</th><th>Total Revenue</th></tr></thead>
                        <tbody>
                            <?php $rank = 1; while($item = $top_items_report->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:700;">#<?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td style="font-weight:700; color:#8B0000;"><?php echo $item['total_sold']; ?> pcs/servings</td>
                                <td style="font-weight:700;">₱<?php echo number_format($item['revenue'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center;color:#94a3b8;padding:2rem;">No sales data available yet.</p>
                <?php endif; ?>
            </div>

            <div class="admin-card" style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;"><i class="fas fa-calendar-alt" style="color:#8B0000;"></i> Sales Breakdown by Month</h3>
                <?php if (empty($monthly_data)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 2rem;">No order data available yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Month / Year</th><th>Total Orders</th><th>Total Revenue</th></tr></thead>
                        <tbody>
                            <?php $grandTotal = 0; $grandOrders = 0; foreach ($monthly_data as $data): $grandTotal += $data['revenue']; $grandOrders += $data['total_orders']; ?>
                                <tr>
                                    <td style="font-weight: 700;"><?php echo $data['month_year']; ?></td>
                                    <td><?php echo $data['total_orders']; ?> orders</td>
                                    <td style="font-weight: 700; color: #8B0000;">₱<?php echo number_format($data['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: 800;">
                                <td>GRAND TOTAL</td>
                                <td><?php echo $grandOrders; ?> orders</td>
                                <td style="color: #8B0000; font-size: 1.1rem;">₱<?php echo number_format($grandTotal, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>
        function closeSidebar() { document.getElementById('sidebar').classList.remove('active'); document.getElementById('sidebarOverlay').classList.remove('active'); document.body.style.overflow = ''; }
    </script>
</body>
</html>