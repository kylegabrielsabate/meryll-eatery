<?php
session_start();
include '../connect.php';

// AUTH GATE
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch all orders for this user, newest first
$stmt = $con->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
    FROM orders o 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// If viewing a specific order, fetch its items
$order_details = null;
if (isset($_GET['order']) && is_numeric($_GET['order'])) {
    $order_id = intval($_GET['order']);
    $stmt = $con->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order_details = $stmt->get_result()->fetch_assoc();
    
    if ($order_details) {
        $stmt2 = $con->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC");
        $stmt2->bind_param("i", $order_id);
        $stmt2->execute();
        $order_details['items'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--brand-red:#8B0000;--brand-light:#fff0f0;--bg-light:#f8f9fa;--text-dark:#333;--text-muted:#6c757d;--sidebar-width:260px;--border-color:#e9ecef}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg-light);display:flex;min-height:100vh}

/* Sidebar (same as index.php) */
.sidebar{width:var(--sidebar-width);background:#fff;border-right:1px solid var(--border-color);display:flex;flex-direction:column;height:100vh;position:fixed;left:0;top:0;z-index:100}
.logo{font-size:1.4rem;font-weight:700;color:var(--brand-red);padding:1.2rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:10px}
.nav-section{padding:1rem 0;flex:1;overflow-y:auto}
.nav-link{display:flex;align-items:center;gap:12px;padding:12px 1.5rem;color:var(--text-dark);text-decoration:none;font-weight:500;font-size:.95rem;transition:all .2s;border-left:3px solid transparent}
.nav-link:hover{background:#f8f9fa}
.nav-link.active{background:var(--brand-light);color:var(--brand-red);border-left:3px solid var(--brand-red);font-weight:600}
.nav-link i{font-size:1.1rem;width:20px;text-align:center;color:var(--text-muted)}
.nav-link.active i{color:var(--brand-red)}
.user-section{padding:1rem 1.5rem;border-top:1px solid var(--border-color);font-size:.9rem;display:flex;align-items:center;gap:8px}
.user-section a{color:var(--brand-red);text-decoration:none;font-weight:600;margin-left:auto}

.main-content{flex:1;margin-left:var(--sidebar-width);padding:2rem}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
.page-header h1{font-size:1.8rem;color:var(--text-dark)}
.back-btn{color:var(--brand-red);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:5px}

/* Order Cards */
.order-card{background:#fff;border-radius:12px;padding:1.5rem;margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid var(--border-color);transition:all .2s}
.order-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
.order-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:10px}
.order-number{font-size:1.1rem;font-weight:700;color:var(--brand-red)}
.order-date{font-size:.85rem;color:var(--text-muted)}
.order-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1rem}
.meta-item{font-size:.9rem}
.meta-label{color:var(--text-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.meta-value{font-weight:600;color:var(--text-dark)}
.order-footer{display:flex;justify-content:space-between;align-items:center;padding-top:1rem;border-top:1px solid var(--border-color)}
.order-total{font-size:1.2rem;font-weight:700;color:var(--brand-red)}
.view-btn{background:var(--brand-red);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;font-size:.9rem}
.view-btn:hover{background:#6d0000}

/* Status Badges */
.status-badge{display:inline-block;padding:5px 12px;border-radius:20px;font-size:.8rem;font-weight:600}
.status-Pending{background:#fff3cd;color:#856404}
.status-Preparing{background:#cce5ff;color:#004085}
.status-Ready{background:#d4edda;color:#155724}
.status-Completed{background:#d1ecf1;color:#0c5460}
.status-Cancelled{background:#f8d7da;color:#721c24}

/* Order Detail View */
.detail-view{background:#fff;border-radius:12px;padding:2rem;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.detail-header{border-bottom:2px solid var(--border-color);padding-bottom:1rem;margin-bottom:1.5rem}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem}
@media(max-width:768px){.detail-grid{grid-template-columns:1fr}}
.detail-box{background:var(--bg-light);padding:1rem;border-radius:8px}
.detail-box h3{font-size:1rem;color:var(--brand-red);margin-bottom:.8rem}
.items-table{width:100%;border-collapse:collapse;margin-top:1rem}
.items-table th{background:var(--brand-red);color:#fff;padding:10px;text-align:left;font-size:.85rem}
.items-table td{padding:10px;border-bottom:1px solid var(--border-color);font-size:.9rem}
.items-table .text-right{text-align:right}
.total-row{font-weight:700;font-size:1.1rem;background:var(--brand-light)}
.total-row td{padding:12px 10px;border-top:2px solid var(--brand-red)}

.empty-state{text-align:center;padding:4rem 2rem;color:var(--text-muted)}
.empty-state i{font-size:4rem;color:#ddd;margin-bottom:1rem}
.empty-state h2{font-size:1.3rem;margin-bottom:.5rem;color:var(--text-dark)}
.empty-state a{color:var(--brand-red);text-decoration:none;font-weight:600}

@media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform .3s}.sidebar.open{transform:translateX(0)}.main-content{margin-left:0}.mobile-toggle{display:block!important}}
.mobile-toggle{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-dark)}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="bi bi-building"></i> Meryll Eatery</div>
    <div class="nav-section">
        <a href="index.php" class="nav-link"><i class="bi bi-calendar-day"></i> Today's Menu</a>
        <a href="my_orders.php" class="nav-link active"><i class="bi bi-receipt"></i> My Orders</a>
        <a href="cart.php" class="nav-link"><i class="bi bi-basket3"></i> Cart</a>
    </div>
    <div class="user-section">
        <i class="bi bi-person-circle"></i> <span><?=htmlspecialchars($username)?></span>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-receipt"></i> My Orders</h1>
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Menu</a>
    </div>

    <?php if ($order_details): ?>
    <!-- ============ ORDER DETAIL VIEW ============ -->
    <div class="detail-view">
        <div class="detail-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <div style="font-size:.85rem;color:var(--text-muted)">Order Number</div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--brand-red)"><?=htmlspecialchars($order_details['order_number'])?></div>
                </div>
                <span class="status-badge status-<?=str_replace(' ','',$order_details['status'])?>"><?=htmlspecialchars($order_details['status'])?></span>
            </div>
        </div>

        <div class="detail-grid">
            <div class="detail-box">
                <h3><i class="bi bi-info-circle"></i> Order Info</h3>
                <div class="meta-item"><div class="meta-label">Order Type</div><div class="meta-value"><?=htmlspecialchars($order_details['order_type'])?></div></div>
                <div class="meta-item" style="margin-top:10px"><div class="meta-label">Preferred Time</div><div class="meta-value"><?=htmlspecialchars($order_details['pickup_time'])?></div></div>
                <div class="meta-item" style="margin-top:10px"><div class="meta-label">Payment</div><div class="meta-value"><?=htmlspecialchars($order_details['payment_method'])?></div></div>
                <div class="meta-item" style="margin-top:10px"><div class="meta-label">Placed On</div><div class="meta-value"><?=date('M d, Y h:i A', strtotime($order_details['created_at']))?></div></div>
            </div>
            <div class="detail-box">
                <h3><i class="bi bi-chat-left-text"></i> Special Instructions</h3>
                <?php if (!empty($order_details['special_instructions'])): ?>
                <p style="color:var(--text-dark);font-size:.9rem;line-height:1.5"><?=nl2br(htmlspecialchars($order_details['special_instructions']))?></p>
                <?php else: ?>
                <p style="color:var(--text-muted);font-size:.9rem">No special instructions</p>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="color:var(--brand-red);margin-bottom:1rem"><i class="bi bi-list-ul"></i> Order Items</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_details['items'] as $item): ?>
                <tr>
                    <td><?=htmlspecialchars($item['item_name'])?></td>
                    <td class="text-right"><?=intval($item['quantity'])?></td>
                    <td class="text-right">₱<?=number_format($item['price'],2)?></td>
                    <td class="text-right">₱<?=number_format($item['price'] * $item['quantity'],2)?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL</td>
                    <td class="text-right">₱<?=number_format($order_details['total_amount'],2)?></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:1.5rem;text-align:center">
            <a href="my_orders.php" class="view-btn"><i class="bi bi-arrow-left"></i> Back to Orders</a>
        </div>
    </div>

    <?php else: ?>
    <!-- ============ ORDERS LIST VIEW ============ -->
    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <i class="bi bi-bag-x"></i>
        <h2>No Orders Yet</h2>
        <p>You haven't placed any orders yet. Start browsing our menu!</p>
        <a href="index.php" class="view-btn" style="margin-top:1rem;display:inline-block"><i class="bi bi-shop"></i> Browse Menu</a>
    </div>
    <?php else: ?>
    <?php foreach ($orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <div>
                <div class="order-number">#<?=htmlspecialchars($order['order_number'])?></div>
                <div class="order-date"><?=date('M d, Y • h:i A', strtotime($order['created_at']))?></div>
            </div>
            <span class="status-badge status-<?=str_replace(' ','',$order['status'])?>"><?=htmlspecialchars($order['status'])?></span>
        </div>
        <div class="order-meta">
            <div class="meta-item">
                <div class="meta-label">Order Type</div>
                <div class="meta-value"><?=htmlspecialchars($order['order_type'])?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Preferred Time</div>
                <div class="meta-value"><?=htmlspecialchars($order['pickup_time'])?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Items</div>
                <div class="meta-value"><?=intval($order['item_count'])?> item(s)</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Payment</div>
                <div class="meta-value"><?=htmlspecialchars($order['payment_method'])?></div>
            </div>
        </div>
        <div class="order-footer">
            <div class="order-total">₱<?=number_format($order['total_amount'],2)?></div>
            <a href="my_orders.php?order=<?=$order['order_id']?>" class="view-btn"><i class="bi bi-eye"></i> View Details</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</main>

<script>
document.querySelector('.mobile-toggle')?.addEventListener('click', function(){
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>