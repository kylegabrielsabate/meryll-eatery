<?php
session_start();
include '../connect.php';
include '../mail_config.php';

// AUTH GATE
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user's email
$stmt = $con->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_email = $stmt->get_result()->fetch_assoc()['email'];

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Build order summary from cart
$items = [];
$total = 0;
foreach ($_SESSION['cart'] as $key => $item) {
    $items[] = [
        'name' => $item['name'],
        'price' => $item['price'],
        'qty' => $item['quantity'],
        'subtotal' => $item['price'] * $item['quantity']
    ];
    $total += $item['price'] * $item['quantity'];
}

$error = '';
$order_success = null;
$success_details = [];

// ============ PROCESS CHECKOUT ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($items)) {
    $fulfillment = $_POST['fulfillment'] ?? '';
    $pickup_time = trim($_POST['pickup_time'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $payment     = $_POST['payment'] ?? 'Cash';

    if (!in_array($fulfillment, ['Dine-in', 'Delivered', 'Pick-up'])) {
        $error = 'Please choose a fulfillment method.';
    } elseif (empty($pickup_time)) {
        $error = 'Please specify your preferred time.';
    } else {
        $con->begin_transaction();
        try {
            // Generate unique Order Number
            $order_number = 'KB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $status = 'Pending';

            // Insert order
            $stmt = $con->prepare("INSERT INTO orders (user_id, order_number, order_type, pickup_time, payment_method, total_amount, status, special_instructions) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssdss", $user_id, $order_number, $fulfillment, $pickup_time, $payment, $total, $status, $notes);
            $stmt->execute();
            $order_id = $con->insert_id;

            // Insert order items
            $stmt2 = $con->prepare("INSERT INTO order_items (order_id, item_name, quantity, price) VALUES (?,?,?,?)");
            foreach ($items as $it) {
                $stmt2->bind_param("isid", $order_id, $it['name'], $it['qty'], $it['price']);
                $stmt2->execute();
            }

            $con->commit();

            // Deduct fried rice stock if applicable
            foreach ($items as $it) {
                if (stripos($it['name'], 'Fried Rice') !== false) {
                    if ($_SESSION['fried_rice_stock'] > 0) $_SESSION['fried_rice_stock']--;
                }
            }

            // ========== SEND CONFIRMATION EMAIL ==========
            $email_subject = "Order Confirmation - " . $order_number;
            
            $email_html = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; overflow: hidden; }
                    .header { background: #8B0000; color: #fff; padding: 25px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .content { padding: 25px; }
                    .greeting { font-size: 16px; margin-bottom: 20px; }
                    .order-box { background: #f8f9fa; border-left: 4px solid #8B0000; padding: 15px; margin: 20px 0; }
                    .order-number { font-size: 20px; font-weight: bold; color: #8B0000; margin-bottom: 10px; }
                    .detail-row { display: flex; justify-content: space-between; margin: 8px 0; font-size: 14px; }
                    .detail-label { font-weight: 600; color: #555; }
                    .detail-value { color: #333; }
                    .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .items-table th { background: #8B0000; color: #fff; padding: 12px; text-align: left; font-size: 14px; }
                    .items-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
                    .items-table .qty { text-align: center; }
                    .items-table .price { text-align: right; }
                    .total-row { font-weight: bold; font-size: 16px; background: #f8f9fa; }
                    .total-row td { padding: 15px 12px; border-top: 2px solid #8B0000; }
                    .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; }
                    .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
                    .badge-dine { background: #d4edda; color: #155724; }
                    .badge-pickup { background: #fff3cd; color: #856404; }
                    .badge-delivered { background: #cce5ff; color: #004085; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🍽️ Meryll Eatery</h1>
                        <p style='margin: 5px 0 0 0; opacity: 0.9;'>Order Confirmation</p>
                    </div>
                    <div class='content'>
                        <div class='greeting'>
                            Hi <b>" . htmlspecialchars($username) . "</b>,<br>
                            Thank you for your order! Here are your order details:
                        </div>
                        
                        <div class='order-box'>
                            <div class='order-number'>📋 Order #" . htmlspecialchars($order_number) . "</div>
                            <div class='detail-row'>
                                <span class='detail-label'>Status:</span>
                                <span class='detail-value'><span class='badge badge-dine'>Pending</span></span>
                            </div>
                            <div class='detail-row'>
                                <span class='detail-label'>Order Type:</span>
                                <span class='detail-value'>" . htmlspecialchars($fulfillment) . "</span>
                            </div>
                            <div class='detail-row'>
                                <span class='detail-label'>Preferred Time:</span>
                                <span class='detail-value'>" . htmlspecialchars($pickup_time) . "</span>
                            </div>
                            <div class='detail-row'>
                                <span class='detail-label'>Payment Method:</span>
                                <span class='detail-value'>" . htmlspecialchars($payment) . "</span>
                            </div>
                        </div>

                        <h3 style='margin: 25px 0 15px 0; color: #333;'>📝 Order Items</h3>
                        <table class='items-table'>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class='qty'>Qty</th>
                                    <th class='price'>Price</th>
                                    <th class='price'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>";
            
            foreach ($items as $it) {
                $email_html .= "
                                <tr>
                                    <td>" . htmlspecialchars($it['name']) . "</td>
                                    <td class='qty'>" . intval($it['qty']) . "</td>
                                    <td class='price'>₱" . number_format($it['price'], 2) . "</td>
                                    <td class='price'>₱" . number_format($it['subtotal'], 2) . "</td>
                                </tr>";
            }
            
            $email_html .= "
                                <tr class='total-row'>
                                    <td colspan='3' style='text-align: right;'>TOTAL:</td>
                                    <td class='price' style='color: #8B0000; font-size: 18px;'>₱" . number_format($total, 2) . "</td>
                                </tr>
                            </tbody>
                        </table>";
            
            if (!empty($notes)) {
                $email_html .= "
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0;'>
                            <b>📌 Special Instructions:</b><br>
                            " . htmlspecialchars($notes) . "
                        </div>";
            }
            
            $email_html .= "
                        <div style='background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                            <b>ℹ️ What's Next?</b><br>
                            <ul style='margin: 10px 0; padding-left: 20px;'>
                                <li>Our kitchen staff will prepare your order</li>
                                <li>You'll receive updates on your order status</li>
                                <li>Show your <b>Order Number</b> at the counter when picking up</li>
                                <li>For dine-in, proceed to the canteen at your preferred time</li>
                            </ul>
                        </div>
                        
                        <p style='color: #777; font-size: 13px; margin-top: 25px;'>
                            If you have any questions about your order, please contact us at the canteen.
                        </p>
                    </div>
                    <div class='footer'>
                        <p><b>Meryll Eatery</b><br>
                        Serving delicious meals with care!<br>
                        This is an automated confirmation email.</p>
                    </div>
                </div>
            </body>
            </html>";

            // Send the email
            $email_sent = sendMail($user_email, $email_subject, $email_html);

            // Clear cart
            $_SESSION['cart'] = [];
            
            $order_success = $order_number;
            $success_details = [
                'fulfillment' => $fulfillment,
                'time' => $pickup_time,
                'payment' => $payment,
                'notes' => $notes,
                'total' => $total,
                'items' => $items,
                'email_sent' => $email_sent['success']
            ];
        } catch (Exception $e) {
            $con->rollback();
            $error = 'Checkout failed. Please try again.';
        }
    }
}

// Empty cart & not just ordered → back to cart
if (empty($items) && !$order_success) { header('Location: cart.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--brand-red:#8B0000;--brand-light:#fff0f0;--bg-light:#f8f9fa;--text-dark:#333;--text-muted:#6c757d;--border-color:#e9ecef}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg-light);min-height:100vh;padding:2rem 1rem}
.checkout-wrap{max-width:900px;margin:0 auto}
.checkout-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
.checkout-header h1{font-size:1.8rem;color:var(--brand-red)}
.back-btn{color:var(--brand-red);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:5px}
.grid{display:grid;grid-template-columns:1.2fr .8fr;gap:1.5rem}
@media(max-width:768px){.grid{grid-template-columns:1fr}}
.card{background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:1.5rem}
.card h2{font-size:1.1rem;color:var(--text-dark);margin-bottom:1rem;display:flex;align-items:center;gap:8px}
.card h2 i{color:var(--brand-red)}
.fulfill-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}
@media(max-width:600px){.fulfill-grid{grid-template-columns:1fr}}
.fulfill-option{border:2px solid var(--border-color);border-radius:12px;padding:1rem;text-align:center;cursor:pointer;transition:all .2s;background:#fff}
.fulfill-option:hover{border-color:var(--brand-red)}
.fulfill-option.selected{border-color:var(--brand-red);background:var(--brand-light)}
.fulfill-option i{font-size:1.8rem;color:var(--brand-red);margin-bottom:.4rem;display:block}
.fulfill-option .f-name{font-weight:700;font-size:.95rem;color:var(--text-dark)}
.fulfill-option .f-desc{font-size:.75rem;color:var(--text-muted);margin-top:.2rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-weight:600;font-size:.85rem;margin-bottom:.4rem;color:var(--text-dark)}
.form-group input,.form-group textarea{width:100%;padding:11px 14px;border:1px solid var(--border-color);border-radius:8px;font-size:.95rem;outline:none;font-family:inherit}
.form-group input:focus,.form-group textarea:focus{border-color:var(--brand-red)}
.form-group textarea{resize:vertical;min-height:80px}
.note-hint{font-size:.75rem;color:var(--text-muted);margin-top:.3rem}
.pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
.pay-option{border:2px solid var(--border-color);border-radius:10px;padding:.9rem;text-align:center;cursor:pointer;transition:all .2s;background:#fff}
.pay-option:hover{border-color:var(--brand-red)}
.pay-option.selected{border-color:var(--brand-red);background:var(--brand-light)}
.pay-option i{font-size:1.4rem;color:var(--brand-red);margin-right:6px}
.pay-option span{font-weight:600;font-size:.9rem}
.summary-item{display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border-color);font-size:.9rem}
.summary-item .s-name{color:var(--text-dark);font-weight:500;flex:1}
.summary-item .s-qty{color:var(--text-muted);margin:0 .8rem;white-space:nowrap}
.summary-item .s-sub{font-weight:600;color:var(--text-dark);white-space:nowrap}
.summary-total{display:flex;justify-content:space-between;padding-top:1rem;font-size:1.2rem;font-weight:700;color:var(--brand-red)}
.place-order-btn{width:100%;padding:14px;background:var(--brand-red);color:#fff;border:none;border-radius:10px;font-size:1.05rem;font-weight:700;cursor:pointer;margin-top:1rem}
.place-order-btn:hover{background:#6d0000}
.success-box{background:#fff;border-radius:16px;padding:3rem 2rem;text-align:center;max-width:560px;margin:3rem auto;box-shadow:0 10px 40px rgba(0,0,0,.1)}
.success-box .big-icon{font-size:4rem;color:#2ecc71;margin-bottom:1rem}
.success-box h1{font-size:1.6rem;color:var(--text-dark);margin-bottom:.5rem}
.claim-code{background:var(--brand-light);border:2px dashed var(--brand-red);border-radius:12px;padding:1rem;margin:1.5rem 0;font-size:1.6rem;font-weight:800;color:var(--brand-red);letter-spacing:2px}
.success-details{text-align:left;background:var(--bg-light);border-radius:10px;padding:1rem;font-size:.9rem;color:var(--text-dark);margin-bottom:1.5rem}
.success-details div{margin-bottom:.4rem}
.success-details b{color:var(--brand-red)}
.alert-error{background:#fee;color:#c00;border:1px solid #fcc;padding:12px 15px;border-radius:8px;margin-bottom:1rem;font-size:.9rem}
.email-status{background:#e7f3ff;border-left:4px solid #007bff;padding:12px 15px;border-radius:8px;margin-bottom:1rem;font-size:.9rem}
.email-status.success{background:#d4edda;border-left-color:#28a745;color:#155724}
.email-status.error{background:#f8d7da;border-left-color:#dc3545;color:#721c24}
.toast{position:fixed;bottom:30px;right:30px;background:var(--brand-red);color:#fff;padding:14px 24px;border-radius:10px;font-weight:600;transform:translateY(100px);opacity:0;transition:all .3s;z-index:9999}
.toast.show{transform:translateY(0);opacity:1}
</style>
</head>
<body>

<?php if ($order_success): ?>
<!-- ============ SUCCESS / CONFIRMATION ============ -->
<div class="success-box">
    <div class="big-icon"><i class="bi bi-check-circle-fill"></i></div>
    <h1>Order Placed Successfully!</h1>
    <p style="color:var(--text-muted)">A confirmation email with your receipt has been sent to <b><?=htmlspecialchars($user_email)?></b></p>
    <div class="claim-code"><?=htmlspecialchars($order_success)?></div>
    <div class="success-details">
        <div><b>Fulfillment:</b> <?=htmlspecialchars($success_details['fulfillment'])?></div>
        <div><b>Preferred Time:</b> <?=htmlspecialchars($success_details['time'])?></div>
        <div><b>Payment:</b> <?=htmlspecialchars($success_details['payment'])?></div>
        <?php if (!empty($success_details['notes'])): ?>
        <div><b>Notes:</b> <?=htmlspecialchars($success_details['notes'])?></div>
        <?php endif; ?>
        <div><b>Total:</b> <?=number_format($success_details['total'],2)?></div>
        <?php if (!$success_details['email_sent']): ?>
        <div style="margin-top:10px;padding:10px;background:#fff3cd;border-radius:6px;font-size:.85rem;color:#856404">
            <i class="bi bi-exclamation-triangle"></i> Note: We couldn't send the confirmation email, but your order was saved successfully!
        </div>
        <?php endif; ?>
    </div>
    <a href="index.php" class="place-order-btn" style="display:block;text-decoration:none;text-align:center">Back to Menu</a>
</div>

<?php else: ?>
<!-- ============ CHECKOUT FORM ============ -->
<div class="checkout-wrap">
    <div class="checkout-header">
        <h1><i class="bi bi-bag-check"></i> Checkout</h1>
        <a href="cart.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Cart</a>
    </div>

    <?php if ($error): ?><div class="alert-error"><i class="bi bi-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

    <form method="POST" action="" id="checkoutForm">
        <div class="grid">
            <!-- LEFT: Options -->
            <div>
                <!-- Fulfillment -->
                <div class="card">
                    <h2><i class="bi bi-signpost-split"></i> How do you want your order?</h2>
                    <div class="fulfill-grid">
                        <div class="fulfill-option" data-value="Dine-in" onclick="selectFulfillment(this)">
                            <i class="bi bi-shop"></i>
                            <div class="f-name">Dine-in</div>
                            <div class="f-desc">Skip the line, eat at canteen</div>
                        </div>
                        <div class="fulfill-option" data-value="Delivered" onclick="selectFulfillment(this)">
                            <i class="bi bi-bag-check"></i>
                            <div class="f-name">Delivered to You</div>
                            <div class="f-desc">Take-out / brought to you</div>
                        </div>
                        <div class="fulfill-option" data-value="Pick-up" onclick="selectFulfillment(this)">
                            <i class="bi bi-geo-alt"></i>
                            <div class="f-name">Pick-up</div>
                            <div class="f-desc">Claim at the counter</div>
                        </div>
                    </div>
                    <input type="hidden" name="fulfillment" id="fulfillmentInput" value="">
                </div>

                <!-- Time -->
                <div class="card">
                    <h2><i class="bi bi-clock"></i> Preferred Time</h2>
                    <div class="form-group">
                        <label>When do you want it ready? *</label>
                        <input type="time" name="pickup_time" required>
                        <div class="note-hint">Example: 12:00 for lunch break</div>
                    </div>
                </div>

                <!-- Notes / Comments -->
                <div class="card">
                    <h2><i class="bi bi-chat-left-text"></i> Notes / Comments <span style="font-weight:400;color:var(--text-muted)">(optional)</span></h2>
                    <div class="form-group">
                        <textarea name="notes" placeholder="Add any special instructions... e.g. 'No mayonnaise', 'Extra sauce', 'Less spicy', 'Add extra rice'"></textarea>
                        <div class="note-hint">The canteen staff will see these notes when preparing your order.</div>
                    </div>
                </div>

                <!-- Payment -->
                <div class="card">
                    <h2><i class="bi bi-credit-card"></i> Payment Method</h2>
                    <div class="pay-grid">
                        <div class="pay-option selected" data-value="Cash" onclick="selectPayment(this)">
                            <i class="bi bi-cash-coin"></i><span>Cash</span>
                        </div>
                        <div class="pay-option" data-value="GCash" onclick="selectPayment(this)">
                            <i class="bi bi-phone"></i><span>GCash</span>
                        </div>
                    </div>
                    <input type="hidden" name="payment" id="paymentInput" value="Cash">
                </div>
            </div>

            <!-- RIGHT: Summary -->
            <div>
                <div class="card">
                    <h2><i class="bi bi-receipt"></i> Order Summary</h2>
                    <?php foreach ($items as $it): ?>
                    <div class="summary-item">
                        <span class="s-name"><?=htmlspecialchars($it['name'])?></span>
                        <span class="s-qty">x<?=intval($it['qty'])?></span>
                        <span class="s-sub">₱<?=number_format($it['subtotal'],2)?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Total</span>
                        <span>₱<?=number_format($total,2)?></span>
                    </div>
                    <button type="submit" class="place-order-btn">
                        <i class="bi bi-bag-check"></i> Place Order
                    </button>
                    <p style="margin-top:10px;font-size:.85rem;color:var(--text-muted);text-align:center">
                        <i class="bi bi-envelope"></i> Confirmation email will be sent to: <b><?=htmlspecialchars($user_email)?></b>
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="toast" id="toast"></div>

<script>
function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500)}

function selectFulfillment(el){
    document.querySelectorAll('.fulfill-option').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('fulfillmentInput').value = el.dataset.value;
}
function selectPayment(el){
    document.querySelectorAll('.pay-option').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('paymentInput').value = el.dataset.value;
}

document.getElementById('checkoutForm')?.addEventListener('submit', function(e){
    const f = document.getElementById('fulfillmentInput').value;
    if (!f) {
        e.preventDefault();
        showToast('⚠️ Please choose Dine-in, Delivered, or Pick-up!');
    }
});
</script>
</body>
</html>