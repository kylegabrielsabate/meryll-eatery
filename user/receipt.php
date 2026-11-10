<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header('Location: index.php');
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// Fetch order details
$stmt = $con->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: my_orders.php');
    exit();
}

// Fetch order items
$stmt = $con->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

// Fetch order status
$stmt = $con->prepare("SELECT status FROM order_status WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$status_row = $stmt->get_result()->fetch_assoc();
$order_status = $status_row ? $status_row['status'] : 'Pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Receipt - #<?=$order['order_number']?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--brand-red:#8B0000;--brand-light:#fff0f0;--bg-light:#f8f9fa;--text-dark:#333;--text-muted:#6c757d}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg-light);min-height:100vh;padding:2rem}
.receipt-container{max-width:800px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);overflow:hidden}
.receipt-header{background:var(--brand-red);color:#fff;padding:2rem;text-align:center}
.receipt-header h1{font-size:2rem;margin-bottom:0.5rem}
.receipt-header p{opacity:0.9}
.receipt-body{padding:2rem}
.receipt-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:1px solid #eee}
.receipt-section:last-child{border-bottom:none}
.section-title{font-size:1.1rem;font-weight:700;color:var(--brand-red);margin-bottom:1rem;display:flex;align-items:center;gap:8px}
.order-info{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
.info-item{display:flex;flex-direction:column}
.info-label{font-size:0.85rem;color:var(--text-muted);margin-bottom:0.25rem}
.info-value{font-size:1rem;font-weight:600;color:var(--text-dark)}
.items-table{width:100%;border-collapse:collapse}
.items-table th{text-align:left;padding:12px;border-bottom:2px solid var(--brand-red);font-weight:600;color:var(--brand-red)}
.items-table td{padding:12px;border-bottom:1px solid #eee}
.items-table .text-right{text-align:right}
.items-table .text-center{text-align:center}
.total-row{font-size:1.3rem;font-weight:700;color:var(--brand-red)}
.status-badge{display:inline-block;padding:6px 16px;border-radius:20px;font-size:0.85rem;font-weight:600}
.status-pending{background:#fff3cd;color:#856404}
.status-preparing{background:#cfe2ff;color:#084298}
.status-ready{background:#d1e7dd;color:#0f5132}
.status-completed{background:#d4edda;color:#155724}
.btn-group{display:flex;gap:1rem;justify-content:center;margin-top:2rem}
.btn{padding:12px 24px;border-radius:8px;border:none;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.btn-primary{background:var(--brand-red);color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.btn:hover{opacity:0.9}
.print-btn{position:fixed;top:20px;right:20px;background:#fff;border:1px solid #ddd;padding:10px 16px;border-radius:8px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.print-btn:hover{background:#f8f9fa}
@media print{
    .print-btn{display:none}
    body{background:#fff;padding:0}
    .receipt-container{box-shadow:none}
}
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()"><i class="bi bi-printer"></i> Print Receipt</button>

<div class="receipt-container">
    <div class="receipt-header">
        <h1><i class="bi bi-receipt"></i> Meryll Eatery</h1>
        <p>Order Receipt</p>
    </div>
    
    <div class="receipt-body">
        <!-- Order Info -->
        <div class="receipt-section">
            <div class="section-title"><i class="bi bi-info-circle"></i> Order Information</div>
            <div class="order-info">
                <div class="info-item">
                    <span class="info-label">Order Number</span>
                    <span class="info-value"><?=htmlspecialchars($order['order_number'])?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?=date('F j, Y \a\t g:i A', strtotime($order['created_at']))?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Type</span>
                    <span class="info-value"><?=htmlspecialchars($order['order_type'])?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value"><?=htmlspecialchars($order['payment_method'])?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Pickup Time</span>
                    <span class="info-value"><?=htmlspecialchars($order['pickup_time'])?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="status-badge status-<?=strtolower($order_status)?>"><?=htmlspecialchars($order_status)?></span>
                </div>
            </div>
            <?php if($order['order_note']): ?>
            <div style="margin-top:1rem">
                <span class="info-label">Order Notes</span>
                <p style="margin-top:0.25rem;color:var(--text-dark)"><?=htmlspecialchars($order['order_note'])?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Order Items -->
        <div class="receipt-section">
            <div class="section-title"><i class="bi bi-list-ul"></i> Order Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    while($item = $items->fetch_assoc()): 
                        $subtotal = $item['price'] * $item['quantity'];
                    ?>
                    <tr>
                        <td><?=htmlspecialchars($item['item_name'])?></td>
                        <td class="text-center"><?=$item['quantity']?></td>
                        <td class="text-right">₱<?=number_format($item['price'], 2)?></td>
                        <td class="text-right">₱<?=number_format($subtotal, 2)?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right total-row">Total:</td>
                        <td class="text-right total-row">₱<?=number_format($order['total_amount'], 2)?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Thank You -->
        <div style="text-align:center;padding:2rem;background:var(--brand-light);border-radius:8px">
            <h2 style="color:var(--brand-red);margin-bottom:0.5rem"><i class="bi bi-heart-fill"></i> Thank You!</h2>
            <p style="color:var(--text-muted)">We appreciate your order. Please present this receipt when claiming.</p>
        </div>

        <div class="btn-group">
            <a href="my_orders.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Back to My Orders</a>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-house"></i> Back to Menu</a>
        </div>
    </div>
</div>

</body>
</html>