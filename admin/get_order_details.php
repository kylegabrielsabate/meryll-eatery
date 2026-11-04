<?php
session_start();
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { exit('Unauthorized'); }

$order_id = (int)$_GET['id'];
$stmt = $con->prepare("SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) { echo "<p>Order not found.</p>"; exit; }

$items_stmt = $con->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>

<div style="margin-bottom:1.5rem;">
    <p><strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['username']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>
    <p><strong>Type:</strong> <?php echo htmlspecialchars($order['order_type']); ?> | <strong>Time:</strong> <?php echo htmlspecialchars($order['pickup_time']); ?></p>
    <?php if (!empty($order['special_instructions'])): ?>
        <p style="background:#fff3cd;padding:10px;border-radius:6px;margin-top:10px;"><strong>📌 Notes:</strong> <?php echo htmlspecialchars($order['special_instructions']); ?></p>
    <?php endif; ?>
</div>

<h4 style="margin-bottom:10px;">Items</h4>
<table style="width:100%;border-collapse:collapse;margin-bottom:1.5rem;">
    <thead>
        <tr style="background:#f8f9fa;">
            <th style="padding:8px;text-align:left;">Item</th>
            <th style="padding:8px;text-align:center;">Qty</th>
            <th style="padding:8px;text-align:right;">Price</th>
        </tr>
    </thead>
    <tbody>
        <?php while($item = $items->fetch_assoc()): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;"><?php echo htmlspecialchars($item['item_name']); ?></td>
            <td style="padding:8px;text-align:center;"><?php echo $item['quantity']; ?></td> <!-- ✅ FIXED: was $item['qty'] -->
            <td style="padding:8px;text-align:right;">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td> <!-- ✅ FIXED -->
        </tr>
        <?php endwhile; ?>
        <tr style="font-weight:700;font-size:1.1rem;">
            <td colspan="2" style="padding:12px 8px;text-align:right;">TOTAL:</td>
            <td style="padding:12px 8px;text-align:right;color:var(--brand-red,#8B0000);">₱<?php echo number_format($order['total_amount'], 2); ?></td>
        </tr>
    </tbody>
</table>

<form method="POST" style="display:flex;gap:10px;align-items:center;border-top:1px solid #eee;padding-top:1rem;">
    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
    <input type="hidden" name="update_status" value="1">
    <label style="font-weight:600;">Update Status:</label>
    <select name="new_status" style="padding:8px;border:1px solid #ddd;border-radius:6px;flex:1;">
        <option value="Pending" <?php echo $order['status']=='Pending'?'selected':''; ?>>Pending</option>
        <option value="Preparing" <?php echo $order['status']=='Preparing'?'selected':''; ?>>Preparing</option>
        <option value="Ready for Pick-up" <?php echo $order['status']=='Ready for Pick-up'?'selected':''; ?>>Ready for Pick-up</option>
        <option value="Completed" <?php echo $order['status']=='Completed'?'selected':''; ?>>Completed</option>
        <option value="Cancelled" <?php echo $order['status']=='Cancelled'?'selected':''; ?>>Cancelled</option>
    </select>
    <button type="submit" style="background:var(--brand-red,#8B0000);color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600;">Save</button>
</form>